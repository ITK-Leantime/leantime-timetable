/**
 * Class handles API requests for time table data.
 */

export default class TimeTableApiHandler {

  /**
   * Retrieves ticket data or fetches it from the server.
   *
   * @returns {Promise<Array>} An array of ticket data.
   */
  static async fetchTicketData() {
    fetch('/TimeTable/TimeTable/getAllProjects', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      }
    })
    .then(response => response.json())
    .then(data => {
                  return data.result;
              })
              .catch(error => console.error('Error fetching projects:', error));


      let ticketPromise = fetch('/TimeTable/TimeTable/getAllTickets', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json',
              }
          })
              .then(response => response.json())
              .then(data => {
                  return data.result;
              })
              .catch(error => console.error('Error fetching projects:', error));
      // Wait for all promises to settle
      const promises = [projectPromise, ticketPromise];
      const results = await Promise.allSettled(promises);

      return results;
      /*return results
          .map(result => result.value)
          .sort((a, b) => a.index - b.index); // Sort by index*/
  }

  /**
   * Retrieves a specific ticket data  or fetches it from the server.
   *
   * @param {number} ticketId The ID of the ticket to fetch.
   * @returns {Promise<Object>} An object of ticket data.
   */
  static async fetchTicketDatum(ticketId) {
    let ticketPromise;
    ticketPromise = this.getTicket(ticketId).then((ticket) => {
      ticket = ticket.result;
      const ticketData = {
        isDone: ticket.status === 0,
        id: ticket.id,
        text: ticket.headline,
        type: ticket.type,
        tags: ticket.tags,
        sprintName: ticket.sprintName,
        projectId: ticket.projectId,
        projectName: ticket.projectName,
        editorId: ticket.editorId,
        hoursLeft: ticket.hourRemaining,
        createdDate: ticket.date,
      };

      return ticketData;
    });

    const result = await Promise.allSettled([ticketPromise]);
    return result
      .filter((result) => result.status === "fulfilled")
      .map((result) => result.value)[0];
  }

  static async createNewTicket(ticketName, projectId, userId) {
    return this.callApi("leantime.rpc.tickets.addTicket", {
      values: { headline: ticketName, projectId: projectId, editorId: userId },
    });
  }




  static getAllProjects() {
    return this.callApi("leantime.rpc.projects.getAll", {});
  }
  /**
   * Retrieves all tickets from the LeanTime API.
   * @return {Promise} - Retrieved tickets or error message
   */
  static getAllTickets() {
    return this.callApi("leantime.rpc.tickets.getAll", {});
  }

  static getTicket(id) {
    return this.callApi("leantime.rpc.tickets.getTicket", { id: id });
  }

  /**
   * Sends a JSON-RPC POST request to the specified API endpoint.
   * @param {String} method - The name of the method to be called on the API.
   * @param {Object} params - The parameters to be sent to the API method.
   * @return {Promise} API response or error message
   */
  static callApi(method, params) {
    return new Promise((resolve, reject) => {
      jQuery.ajax({
        url: leantime.appUrl + "/api/jsonrpc/",
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        data: JSON.stringify({
          method: method,
          jsonrpc: "2.0",
          id: "1",
          params: params,
        }),
        success: resolve,
        error: reject,
      });
    });
  }
}
