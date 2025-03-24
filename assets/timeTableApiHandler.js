/**
 * Class handles API requests for time table data.
 */

export default class TimeTableApiHandler {
  static async fetchTicketData() {
    let projectPromise = fetch("/TimeTable/TimeTable/getAllProjects", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        return data.result;
      })
      .catch((error) => console.error("Error fetching projects:", error));

    let ticketPromise = fetch("/TimeTable/TimeTable/getAllTickets", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        return data.result;
      })
      .catch((error) => console.error("Error fetching projects:", error));
    // Wait for all promises to settle
    const promises = [projectPromise, ticketPromise];
    const results = await Promise.allSettled(promises);

    return results;
  }

  static async createNewTicket(ticketName, projectId) {
    let createTicketPromise = fetch("/TimeTable/TimeTable/createNewTicket", {
      method: "POST",
      body: JSON.stringify({ headline: ticketName, projectId: projectId }),
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        return data;
      })
      .catch((error) => console.error("Error fetching projects:", error));
    const results = await Promise.allSettled([createTicketPromise]);
    return results;
  }
}
